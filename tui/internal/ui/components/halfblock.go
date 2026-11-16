package components

import (
	"bytes"
	"image"
	"image/color"
	"strings"

	// Register decoders used by image.Decode.
	_ "image/gif"
	_ "image/jpeg"
	_ "image/png"

	"github.com/charmbracelet/colorprofile"
	"github.com/charmbracelet/x/ansi"
)

// DecodeImage decodes JPEG, PNG or GIF bytes with the standard library.
func DecodeImage(b []byte) (image.Image, error) {
	img, _, err := image.Decode(bytes.NewReader(b))
	return img, err
}

// FitCells returns the terminal cell size (cols x rows) an image of the given
// pixel size occupies when drawn with half blocks (1 cell = 1x2 pixels),
// scaled to fit within maxCols x maxRows while keeping its aspect ratio.
func FitCells(imgW, imgH, maxCols, maxRows int) (cols, rows int) {
	if imgW <= 0 || imgH <= 0 || maxCols <= 0 || maxRows <= 0 {
		return 0, 0
	}
	cols = maxCols
	pxH := cols * imgH / imgW
	if pxH > maxRows*2 {
		pxH = maxRows * 2
		cols = max(1, pxH*imgW/imgH)
	}
	rows = max(1, (pxH+1)/2)
	return cols, rows
}

// HalfBlock renders img as rows of '▀' cells where the foreground colour is
// the upper pixel and the background colour the lower pixel. Colours are
// converted to the given terminal profile so 256/16-colour terminals get the
// nearest palette entry. It returns "" for profiles without colour.
func HalfBlock(img image.Image, maxCols, maxRows int, profile colorprofile.Profile) string {
	if img == nil || profile <= colorprofile.ASCII && profile != colorprofile.Unknown {
		return ""
	}
	if profile == colorprofile.Unknown {
		profile = colorprofile.TrueColor
	}
	b := img.Bounds()
	cols, rows := FitCells(b.Dx(), b.Dy(), maxCols, maxRows)
	if cols == 0 {
		return ""
	}
	pxH := rows * 2
	px := func(x, y int) color.Color {
		x0 := b.Min.X + x*b.Dx()/cols
		x1 := b.Min.X + (x+1)*b.Dx()/cols
		y0 := b.Min.Y + y*b.Dy()/pxH
		y1 := b.Min.Y + (y+1)*b.Dy()/pxH
		return average(img, x0, max(x1, x0+1), y0, max(y1, y0+1))
	}

	var sb strings.Builder
	for row := range rows {
		for col := range cols {
			top := profile.Convert(px(col, row*2))
			bot := profile.Convert(px(col, row*2+1))
			sb.WriteString(ansi.Style{}.ForegroundColor(top).BackgroundColor(bot).String())
			sb.WriteString("▀")
		}
		sb.WriteString(ansi.ResetStyle)
		if row < rows-1 {
			sb.WriteByte('\n')
		}
	}
	return sb.String()
}

// average box-filters a source rectangle, sampling at most 8x8 points.
func average(img image.Image, x0, x1, y0, y1 int) color.Color {
	stepX := max(1, (x1-x0)/8)
	stepY := max(1, (y1-y0)/8)
	var r, g, bl, n uint64
	for y := y0; y < y1; y += stepY {
		for x := x0; x < x1; x += stepX {
			cr, cg, cb, _ := img.At(x, y).RGBA()
			r += uint64(cr)
			g += uint64(cg)
			bl += uint64(cb)
			n++
		}
	}
	if n == 0 {
		return color.Black
	}
	return color.RGBA{R: uint8(r / n >> 8), G: uint8(g / n >> 8), B: uint8(bl / n >> 8), A: 0xff}
}
